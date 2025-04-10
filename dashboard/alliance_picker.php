<?php
// alliance_picker.php

// Database connection configuration
$host = 'localhost';
$dbname = 'frc_scouting';
$username = 'root';
$password = 'pw123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Retrieve unique events from the scouting_submissions table.
try {
    $stmt = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions");
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Error fetching events: " . $e->getMessage());
}

$selectedEvent = isset($_GET['event']) ? $_GET['event'] : "";
$selectedRobot = isset($_GET['robot']) ? $_GET['robot'] : "";
$tbaEventKey = isset($_GET['event_key']) ? $_GET['event_key'] : "";
$analysisData = null;

// If an event has been selected, query for unique robots for that event.
$robots = array();
if (!empty($selectedEvent)) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT robot FROM scouting_submissions WHERE event_name = :event");
        $stmt->execute(['event' => $selectedEvent]);
        $robots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        die("Error fetching robots: " . $e->getMessage());
    }
}

// If all parameters are supplied, call the Flask analysis service.
if (!empty($selectedEvent) && !empty($selectedRobot) && !empty($tbaEventKey)) {
    $analyzeUrl = "http://localhost:9105/analyze?event=" . urlencode($selectedEvent) .
                  "&robot=" . urlencode($selectedRobot) .
                  "&event_key=" . urlencode($tbaEventKey);
    $analysisResponse = file_get_contents($analyzeUrl);
    $analysisData = json_decode($analysisResponse, true);
}

// Extract the year from the TBA Event Key (first 4 characters)
$year = "";
if (!empty($tbaEventKey)) {
    $year = substr($tbaEventKey, 0, 4);
}
// Base URLs for avatars and team pages.
$avatarBase = "https://www.thebluealliance.com/avatar/" . $year . "/frc";
$teamPageBase = "https://www.thebluealliance.com/team/";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Alliance Analysis</title>
  <link rel="stylesheet" href="../css/select.css">
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
        .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }
    .containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }
    .container {
      max-width: 800px;
      margin: auto;
    }
    
    h1, h2 { color: #fff; }
 input[type="submit"], input[type="text"] {
font-size: 1.1rem; /* Increases font size for better readability */
            padding: 12px; /* Adds padding for touch-friendly areas */
            border: 1px solid #fff; /* Adds a white border */
            background-color: #222; /* Sets background color to match the theme */
            color: #fff; /* Sets text color to white */
            border-radius: 5px; /* Rounds the corners */
      margin: 10px 0;
      width: 180px;
    }
    select{ width: 200px;}
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { padding: 8px; text-align: center; border: 1px solid #ddd; }
    th { background-color: #333; }
    img { border-radius: 4px; }
  </style>
</head>
<body>

  <div class="containerOuter">
    <div class="container">
      <a href=".."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>

  <form method="GET" action="alliance_picker.php">
      <label for="event"></label>
      <select name="event" id="event" onchange="this.form.submit()">
          <option value="">-- Select an Event --</option>
          <?php foreach ($events as $ev): ?>
              <option value="<?php echo htmlspecialchars($ev); ?>" <?php if ($ev == $selectedEvent) echo "selected"; ?>>
                  <?php echo htmlspecialchars($ev); ?>
              </option>
          <?php endforeach; ?>
      </select>
 
      
      <?php if (!empty($selectedEvent)): ?>
          <label for="event_key"></label>
          <input type="text" id="event_key" name="event_key" default="Enter TBA Event Key:"value="<?php echo htmlspecialchars($tbaEventKey); ?>" required>
  
          
          <label for="robot"></label>
          <select name="robot" id="robot">
              <option value="">-- Select Your Robot --</option>
              <?php foreach ($robots as $r): ?>
                  <option value="<?php echo htmlspecialchars($r); ?>" <?php if ($r == $selectedRobot) echo "selected"; ?>>
                      <?php echo htmlspecialchars($r); ?>
                  </option>
              <?php endforeach; ?>
          </select>

          <input type="submit" value="Analyze">
      <?php endif; ?>
  </form>
  
  <?php if ($analysisData): ?>
      <h2>First Pick Options</h2>
      <table>
          <thead>
              <tr>
                  <th>Logo</th>
                  <th>Team</th>
                  <th>Rank</th>
                  <th>Predicted Pts/Match</th>
                  <th>Cycle Time (sec)</th>
                  <th>Favorite Scoring</th>
                  <th>Scores Algae</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($analysisData['first_pick_options'] as $opt): ?>
              <tr>
                  <td>
                      <a href="<?php echo $teamPageBase . htmlspecialchars($opt['robot']); ?>">
                          <img src="<?php echo $avatarBase . htmlspecialchars($opt['robot']); ?>.png" width="50" height="50" alt="Logo"
                               onerror="this.onerror=null; this.src='../images/first_logo.png';">
                      </a>
                  </td>
                  <td><?php echo htmlspecialchars($opt['robot']); ?></td>
                  <td><?php echo htmlspecialchars($opt['ranking']); ?></td>
                  <td><?php echo number_format($opt['predicted_avg_pts_per_match'], 2); ?></td>
                  <td><?php echo number_format($opt['baseline_cycle'], 2); ?></td>
                  <td><?php echo htmlspecialchars($opt['most_common_action']); ?></td>
                  <td><?php echo htmlspecialchars($opt['scores_algae']); ?></td>
              </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
      
      <h2>Second Pick Options Offense</h2>
      <table>
          <thead>
              <tr>
                  <th>Logo</th>
                  <th>Team</th>
                  <th>Rank</th>
                  <th>Predicted Pts/Match</th>
                  <th>Cycle Time (sec)</th>
                  <th>Favorite Scoring</th>
                  <th>Scores Algae</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($analysisData['second_pick_options_offense'] as $opt): ?>
              <tr>
                  <td>
                      <a href="<?php echo $teamPageBase . htmlspecialchars($opt['robot']); ?>">
                          <img src="<?php echo $avatarBase . htmlspecialchars($opt['robot']); ?>.png" width="50" height="50" alt="Logo"
                               onerror="this.onerror=null; this.src='../images/first_logo.png';">
                      </a>
                  </td>
                  <td><?php echo htmlspecialchars($opt['robot']); ?></td>
                  <td><?php echo htmlspecialchars($opt['ranking']); ?></td>
                  <td><?php echo number_format($opt['predicted_avg_pts_per_match'], 2); ?></td>
                  <td><?php echo number_format($opt['baseline_cycle'], 2); ?></td>
                  <td><?php echo htmlspecialchars($opt['most_common_action']); ?></td>
                  <td><?php echo htmlspecialchars($opt['scores_algae']); ?></td>
              </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
      
      <h2>Defensive Options</h2>
      <table>
          <thead>
              <tr>
                  <th>Logo</th>
                  <th>Team</th>
                  <th>Rank</th>
                  <th>Predicted Pts/Match</th>
                  <th>Cycle Time (sec)</th>
                  <th>Defensive Impact (sec)</th>
                  <th>Defensive Effect: Slowdown (sec)</th>
                  <th>Defensive Effect: Points Reduction</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($analysisData['defensive_options'] as $opt): ?>
              <tr>
                  <td>
                      <a href="<?php echo $teamPageBase . htmlspecialchars($opt['robot']); ?>">
                          <img src="<?php echo $avatarBase . htmlspecialchars($opt['robot']); ?>.png" width="50" height="50" alt="Logo"
                               onerror="this.onerror=null; this.src='../images/first_logo.png';">
                      </a>
                  </td>
                  <td><?php echo htmlspecialchars($opt['robot']); ?></td>
                  <td><?php echo htmlspecialchars($opt['ranking']); ?></td>
                  <td><?php echo number_format($opt['predicted_avg_pts_per_match'], 2); ?></td>
                  <td><?php echo number_format($opt['baseline_cycle'], 2); ?></td>
                  <td><?php echo number_format($opt['defensive_impact_delta'], 2); ?></td>
                  <td><?php echo number_format($opt['def_effect_cycle'], 2); ?></td>
                  <td><?php echo number_format($opt['def_effect_points'], 2); ?></td>
              </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
      
      <h2>Full Candidate Analysis</h2>
      <table>
          <thead>
              <tr>
                  <th>Logo</th>
                  <th>Team</th>
                  <th>Explanation</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($analysisData['full_candidate_analysis'] as $detail): ?>
              <tr>
                  <td>
                      <a href="<?php echo $teamPageBase . htmlspecialchars($detail['robot']); ?>">
                          <img src="<?php echo $avatarBase . htmlspecialchars($detail['robot']); ?>.png" width="50" height="50" alt="Logo"
                               onerror="this.onerror=null; this.src='../images/first_logo.png';">
                      </a>
                  </td>
                  <td><?php echo htmlspecialchars($detail['robot']); ?></td>
                  <td><?php echo htmlspecialchars($detail['explanation']); ?></td>
              </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
      
      <h2>Defensive Effects Summary</h2>
      <table>
          <thead>
              <tr>
                  <th>Team (Defending)</th>
                  <th>Opponents Avg Cycle Slowdown (sec)</th>
                  <th>Opponents Avg Points Reduction</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($analysisData['defensive_effects_summary'] as $d): ?>
              <tr>
                  <td><?php echo htmlspecialchars($d['robot']); ?></td>
                  <td><?php echo number_format($d['def_effect_cycle'], 2); ?></td>
                  <td><?php echo number_format($d['def_effect_points'], 2); ?></td>
              </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
  <?php endif; ?>

</div>
</div>

</body>
</html>
