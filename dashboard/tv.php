<?php
// Include the database connection settings and functions
require_once '../php/database_connection.php';

// Create a new PDO connection using the variables defined in the included file.
// The PDO object is used for interacting with the database.
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

// Set the PDO error mode to Exception so that any errors will throw exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
  Fetch a set of event data from the database. The query does the following:
  - Selects the latest record from scouting_submissions (using ORDER BY id DESC LIMIT 1)
  - Joins that record with the active_event table on a condition that:
      * Matches event names (forcing a specific collation if needed)
      * And where the match number in active_event equals scouting_submissions.match_no + 1
  - Orders the results by the alliance in ascending order

  Note: This query assumes that the latest submission (by ID) in scouting_submissions 
        is the one of interest, and then the query uses that event name and match number
        information to join with active_event.
*/
$event_query = $pdo->query("
    SELECT 
      ss.event_name,
      ae.match_number,
      ae.alliance,
      ae.robot
    FROM (
      SELECT event_name, match_no, time_sec
      FROM scouting_submissions
      ORDER BY id DESC
      LIMIT 1
    ) ss
    LEFT JOIN active_event ae
      ON ss.event_name COLLATE utf8mb4_unicode_ci = ae.event_name COLLATE utf8mb4_unicode_ci
      AND ss.match_no + 1 = ae.match_number
    ORDER BY ae.alliance ASC
");

// Fetch all rows as an associative array and store in $events
$events = $event_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Meta tags for proper character encoding and responsive design -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Owl TV</title>
 
  <style>
    /* --- Font Faces --- */
    /* Define custom fonts using @font-face for use in the application */
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

    /* --- Global Styles --- */
    /* Set base styles for the entire document */
    body, html {
      margin: 0;
      padding: 0;
      background: #222;        /* Dark background for the dashboard */
      color: #eee;             /* Light text color for contrast */
      font-family: 'Comfortaa', sans-serif;
      font-size: 16px;
      line-height: 1.5;
    }
    /* Container styling for centering content and adding padding */
    .container {
      margin: auto;
      padding: 1rem;
    }

    /* --- Header Card: Logo & Dropdowns --- */
    /* Styles for the header section that contains the logo and dropdowns */
    .header-card {
      width: 100vw;
      min-height: 4rem;
      padding: 1rem;
      box-sizing: border-box;
      background: #333;
      color: #fff;
      border-radius: 8px;
    }

    /* Special class for the logo card to override background color */
    .logoCard {
      background-color: #222 !important;
    }
    .logo {
      width: 16rem;
      margin-left: auto;
    }
    /* Flex container for dropdowns to ensure they are spaced out and responsive */
    .dropdowns {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 1rem;
      width: 100%;
    }
    /* Styles for each dropdown select element */
    .dropdowns  {
      font-size: .8rem;
      border: 1px solid #555;
      border-radius: 4px;
      background: #444;
      color: #eee;
    }

    /* --- Robot Cards Container (Grid) --- */
    /* Grid layout for robot cards; cards will adjust automatically to the screen width */
    .robot-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, 16rem);
      gap: 1rem;
      justify-content: center;
    }

    /* Additional grid container class */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, 16rem);
      gap: 1rem;
      justify-content: center;
    }

    /* --- Each Robot Card --- */
    /* Card styling for individual robot cards */
    .robot-card {
      width: 16rem;
      height: 16rem;
      background: #fff;
      color: #333;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      position: relative;      /* To allow absolutely positioned inner elements */
      overflow: hidden;        /* Hide overflow for clean card edges */
      /* Note: Random rotation may be applied inline if desired */
    }
    /* --- Top Row (robot name and alliance) --- */
    /* Header section of the card that displays the robot's name and alliance */
    .robot-card .top-row {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      padding: 0.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: rgba(255,255,255,0.9); /* Slightly opaque background for readability */
      z-index: 2;
      box-sizing: border-box;
    }
    /* Styling for text within the top row */
    .robot-card .robot-name, .robot-card .alliance {
      font-weight: bold;
      font-size: 1.2rem;
    }
    /* --- Views Container --- */
    /* Container for different views (stat cards, performance charts, etc.) inside a robot card */
.robot-card .view {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transition: transform 0.5s ease, opacity 0.5s ease;
  opacity: 0;
  z-index: 1;
}
    /* Active view is fully visible */
    .robot-card .view.active {
      transform: translateX(0);
      opacity: 1;
    }
    /* Inactive view is shifted off-screen */
    .robot-card .view.inactive {
      transform: translateX(100%);
      opacity: 0;
    }
    /* --- Stat Cards (View 1) --- */
    /* Grid layout for stat cards inside the robot card */
 .stat-cards {
  display: grid;
  /* Exactly 2 columns and 4 rows, for 8 total cells */
  grid-template-columns: repeat(2, 1fr);
  grid-template-rows: repeat(4, 4rem);
  gap: 0.5rem;
  padding: 0.5rem;
  box-sizing: border-box;
}

.stat-cards {
  display: grid;
  /* 3 columns and 3 rows, forcing 9 cells */
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(3, 1fr);
  gap: 0.5rem;
  padding: 0.2rem;
  width: 100%;
  height: 100%;
  box-sizing: border-box;
}


.stat-card p,
.stat-card h1, .stat-card h2, .stat-card h3, .stat-card h4, .stat-card h5, .stat-card h6 {
  margin: 0;
  padding: 0;
}


.stat-card {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid;
  border-radius: 4px;
  font-size: 0.9rem;
  text-align: center;
  box-sizing: border-box;
  overflow: auto;      /* Allows overflow if needed, or use 'visible' */
  white-space: normal; /* Allows text to wrap */
  /* Remove text-overflow: ellipsis if not needed */
}
/* Example color classes (adjust as needed) */
.stat-card.robot        { background-color: rgba(96,20,55,0.6);   border-color: rgb(96,20,55); }
.stat-card.matches      { background-color: rgba(20,96,61,0.6);   border-color: rgb(20,96,61); }
.stat-card.starting     { background-color: rgba(33,91,159,0.6);  border-color: rgb(33,91,159); }
.stat-card.auton_path   { background-color: rgba(96,23,20,0.6);   border-color: rgb(96,23,20); }
.stat-card.location     { background-color: rgba(20,55,96,0.6);   border-color: rgb(20,55,96); }
.stat-card.offense      { background-color: rgba(96,20,55,0.6);   border-color: rgb(96,20,55); }
.stat-card.defense      { background-color: rgba(96,61,20,0.6);   border-color: rgb(96,61,20); }
.stat-card.auton_score  { background-color: rgba(96,23,20,0.6);   border-color: rgb(96,23,20); }
.stat-card.coop         { background-color: rgba(33,91,159,0.6);  border-color: rgb(33,91,159); }



    /* --- Performance Chart (View 2) --- */
    /* Container for the performance chart */
    .performance-chart {
      padding: 0.5rem;
      box-sizing: border-box;
      height: 100%;
    }
    /* Force canvas to take full available size */
    .performance-chart canvas {
      width: 100% !important;
      height: 100% !important;
    }
    /* --- Scoring Breakdown Table (View 3) --- */
    /* Container for the scoring breakdown table */
    .scoring-breakdown {
      padding: 0.5rem;
      box-sizing: border-box;
      overflow-y: auto;
      height: 100%;
    }
    /* Styles for the table showing scoring breakdown */
    .scoring-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.5rem;
    }
    .scoring-table th, .scoring-table td {
      border: 1px solid #ccc;
      padding: 0.3rem;
      text-align: center;
    }
    .scoring-table th {
      background: #f0f0f0;
      color: #333;
    }
    /* Class for the VS image (used between alliances) */
    .vsImg {
      width: 4rem;
    }

    /* --- Prediction Card --- */
    /* Styling for the prediction card that displays match predictions */
    #predictionCard {
      font-size: 0.75rem;
      display: none; /* Initially hidden until prediction data is available */
      padding-left: 12px;
    }

    /* --- Loader Styles --- */
    /* Loader element for visual feedback while prediction is processing */
    .loader {
      width: 85px;
      height: 50px;
      /* CSS custom properties for gradients */
      --g1: conic-gradient(from 90deg at left 3px top 3px, #0000 90deg, #fff 0);
      --g2: conic-gradient(from -90deg at bottom 3px right 3px, #0000 90deg, #fff 0);
      background: var(--g1), var(--g1), var(--g1), var(--g2), var(--g2), var(--g2);
      background-position: left, center, right;
      background-repeat: no-repeat;
      animation: l10 1s infinite alternate;
    }
    /* Keyframes for loader animation */
    @keyframes l10 {
      0%, 2%   { background-size: 25px 50%, 25px 50%, 25px 50% }
      20%      { background-size: 25px 25%, 25px 50%, 25px 50% }
      40%      { background-size: 25px 100%, 25px 25%, 25px 50% }
      60%      { background-size: 25px 50%, 25px 100%, 25px 25% }
      80%      { background-size: 25px 50%, 25px 50%, 25px 100% }
      98%, 100% { background-size: 25px 50%, 25px 50%, 25px 50% }
    }
    .loader { margin: auto; }
    #predictionCard { border: none; background-color: #222; }

    /* Loader2 styling for additional loader feedback */
    .loader2 {
      color: #fff;
      font-weight: bold;
      font-family: monospace;
      display: inline-grid;
      font-size: 1.5rem;
      margin: auto;
      text-align: center;
    }
    /* Loader2 pseudo-elements for text masking effect */
    .loader2:before,
    .loader2:after {
      content: "Processing Random Forest Regression Prediction...";
      grid-area: 1/1;
      -webkit-mask-size: 2ch 100%, 100% 100%;
      -webkit-mask-repeat: no-repeat;
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      animation: l37 1s infinite;
    }
    .loader2:before {
      -webkit-mask-image:
        linear-gradient(#000 0 0),
        linear-gradient(#000 0 0);
    }
    .loader2:after {
      -webkit-mask-image: linear-gradient(#000 0 0);
      transform: scaleY(0.5);
    }
    /* Keyframes for loader2 text masking animation */
    @keyframes l37 {
      0%    { -webkit-mask-position: 1ch 0, 0 0 }
      12.5% { -webkit-mask-position: 100% 0, 0 0 }
      25%   { -webkit-mask-position: 4ch 0, 0 0 }
      37.5% { -webkit-mask-position: 8ch 0, 0 0 }
      50%   { -webkit-mask-position: 2ch 0, 0 0 }
      62.5% { -webkit-mask-position: 100% 0, 0 0 }
      75%   { -webkit-mask-position: 0ch 0, 0 0 }
      87.5% { -webkit-mask-position: 6ch 0, 0 0 }
      100%  { -webkit-mask-position: 3ch 0, 0 0 }
    }

    /* --- Custom Select Styling --- */
    /* Custom styling for dropdown select elements */
 

  </style>
      <link rel="stylesheet" href="../css/select.css">
</head>
<body>
  <div class="containerOuter">
    <div class="container">
      <!-- Header Card -->
      <div class="card header-card">
        <div class="dropdowns">


          <!-- Dropdowns for selecting alliance robots -->

          <div>
            <label for="blue1"><strong>Blue 1:</strong></label><br>
            <select id="blue1">
              <option>blue 1</option>
            </select>
          </div>
          <div>
            <label for="blue2"><strong>Blue 2:</strong></label><br>
            <select id="blue2">
              <option>blue 2</option>
            </select>
          </div>
          <div>
            <label for="blue3"><strong>Blue 3:</strong></label><br>
            <select id="blue3">
              <option>blue 3</option>
            </select>
          </div>


          


          <div>
            <!-- VS image between alliances -->
            <img class="vsImg" src="../images/vs.png" alt="vs">
          </div>

          
          <div>
            <label for="red1"><strong>Red 1:</strong></label><br>
            <select id="red1">
              <option></option>
            </select>
          </div>
          <div>
            <label for="red2"><strong>Red 2:</strong></label><br>
            <select id="red2">
              <option>red 2</option>
            </select>
          </div>
          <div>
            <label for="blue3"><strong>Red 3:</strong></label><br>
            <select id="red3">
              <option>red 3</option>
            </select>
          </div>

          <!-- Dropdown for sorting robot cards -->
          <div>
            <label for="sortOption"><strong>Sort by:</strong></label><br>
            <select id="sortOption" onchange="updateRobotCards()">
       
              <option value="offense_score" selected>Offense Score</option>
              <option value="defense_score">Defense Score</option>
              <option value="cooperative_score">Cooperative Score</option>
              <option value="robot">Robot</option> <!-- New sort option -->
            </select>
          </div>


        </div>
      </div>
      <!-- Robot Cards Container -->
      <div id="robotContainer" class="robot-cards">
        <!-- Initial placeholder card for the logo -->
        <div class="robot-card logoCard" id="card_1"></div>
      </div>
    </div>
  </div>
  
  <!-- Include Chart.js library for rendering performance charts -->
  <script src="../js/Chart.bundle.js"></script>
  <script>
    // Global variables to store data from API calls and filtering info
    let fetchedRobots = [];  // Array to store robot data fetched from the server
    let aggregatedData = {}; // Object to store prediction data
    let filterRobots = [];   // Array of robot IDs to exclude (for filtering)

    // -----------------------------
    // FETCH MATCHES FUNCTION
    // -----------------------------
    // Fetch match data for a selected event from the server.
    function fetchMatches() {
      // Get the selected event from the dropdown
      const eventName = document.getElementById("eventDropdown").value;
      const matchDropdown = document.getElementById("matchDropdown");
      const container = document.getElementById("robotContainer");

      // Reset dropdown options and container content
      matchDropdown.innerHTML = "<option value=''>-- Select Match --</option><option value='all'>All Matches</option>";
      container.innerHTML = "";

      // If no event is selected, do nothing
      if (!eventName) return;

      // Create an XMLHttpRequest to fetch match data
      const xhr = new XMLHttpRequest();
      xhr.open("GET", "fetch_matches.php?event_name=" + encodeURIComponent(eventName), true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          let response = JSON.parse(xhr.responseText);
          if (response.error) { 
            console.error(response.error); 
            return; 
          }
          // Loop through matches and add each as an option in the dropdown
          if (response.length > 0) {
            response.forEach(match => {
              let option = document.createElement("option");
              option.value = match.match_number;
              option.textContent = "Match " + match.match_number;
              matchDropdown.appendChild(option);
            });
          }
        }
      };
      xhr.send();
    }
    
    // -----------------------------
    // FETCH ROBOT CARDS FUNCTION
    // -----------------------------
    // Fetch robot card data for a given event.
    function fetchRobotCards() {
      // Retrieve event data that was fetched earlier in PHP
      const eventData = <?php echo json_encode($events); ?>;
      const currentEvent = eventData[0];
      const eventName = currentEvent.event_name;
      // For this function, we assume match number is 'all'
      const matchNumber = 'all';

      const container = document.getElementById("robotContainer");
      container.innerHTML = "";
      if (!eventName || !matchNumber) return;

      // Determine which URL to use based on the match number selection
      const xhr = new XMLHttpRequest();
      let url = (matchNumber !== 'all')
          ? "fetch_robot_data.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber)
          : "fetch_robot_data2.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber);
      xhr.open("GET", url, true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          try {
            let data = JSON.parse(xhr.responseText);
            if (data.error) {
              container.innerHTML = `<p style="color:red;">${data.error}</p>`;
              return;
            }
            // Normalize the returned data into an array
            let robotsArray = Array.isArray(data) ? data : (data.robots && Array.isArray(data.robots)) ? data.robots : [data];
            fetchedRobots = robotsArray;
            filterRobots = [];
            // Update robot cards with the fetched data
            updateRobotCards();
            // Populate dropdowns for robot selection
            populateAllRobotDropdowns();
          } catch (error) {
            console.error("JSON Parsing Error:", error);
            container.innerHTML = `<p style="color:red;">Error processing robot data.</p>`;
          }
        }
      };
      xhr.send();
    }
    
    // -----------------------------
    // UPDATE ROBOT CARDS FUNCTION
    // -----------------------------
    // Sorts, filters, and then displays the robot cards.
    function updateRobotCards() {
      const sortBy = document.getElementById("sortOption").value;
      let sorted = [...fetchedRobots];

      // Sort the robots based on the selected option
      if (sortBy === "robot") {
        // Compare robot IDs as strings for proper alphabetical sorting
        sorted.sort((a, b) => String(a.robot).localeCompare(String(b.robot)));
      } else if (sortBy === "alliance") {
        sorted.sort((a, b) => (a.alliance || "Unknown").localeCompare(b.alliance || "Unknown"));
      } else {
        // For numerical stats, sort in descending order
        sorted.sort((a, b) => (b[sortBy] || 0) - (a[sortBy] || 0));
      }

      // Filter out robots that are in the filterRobots array
      const finalList = sorted.filter(robot => !filterRobots.includes(parseInt(robot.robot, 10)));
      // Call function to render the robot cards
      displayRobotCards(finalList);
    }

    // -----------------------------
    // HELPER FUNCTION: FORMAT LOCATION
    // -----------------------------
    // Formats a location string by replacing underscores with spaces and capitalizing the first letter.
    function formatString(str) {
      if (!str) return "N/A";
      str = str.replace(/_/g, " ");
      return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // -----------------------------
    // DISPLAY ROBOT CARDS FUNCTION
    // -----------------------------
    // Builds the HTML for each robot card and renders performance charts.
    function displayRobotCards(robots) {
      const container = document.getElementById("robotContainer");
      if (!container) return;

      // Start with a header/logo card and a prediction card
      let html = `
        <div class="robot-card logoCard" id="card_2">
        <a href="..">  <img class="logo" src="../images/owlanalytics.png" alt="Logo"></a>
        </div>
        <div id="predictionCard" class="card robot-card prediction-card">
          <h3>Match Prediction</h3>
          <p id="predictionResult">Waiting for prediction...</p>
        </div>
      `;

      // Loop through each robot and create its card HTML
      robots.forEach(robot => {
        html += `
          <div class="robot-card">
   
            <!-- View 1: Stat Cards -->
            <div class="view stat-cards active">
          <div class="stat-card robot">Robot <br> ${robot.robot}</div>
              <div class="stat-card matches">Matches Played<br> ${robot.match_count || "N/A"}</div>
          <div class="stat-card starting">Start Position<br> ${(robot.starting_position || "N/A").replace("starting_position_", "")}</div>
          <div class="stat-card auton_path">Auton Path<br> ${(robot.auton_path || "N/A").replace("auton_", "")}</div>
              <div class="stat-card location">Scoring ${formatString(robot.top_scoring_location).replace("Scores", "")}</div>
              <div class="stat-card offense">Offense<br> ${robot.offense_score || 0}</div>
              <div class="stat-card defense">Defense<br> ${robot.defense_score || 0}</div>
              <div class="stat-card auton_score">Auton<br> ${robot.auton_score || 0}</div>
              <div class="stat-card coop">Co-op<br> ${parseFloat(robot.cooperative_score || 0).toFixed(2)}</div>
            </div>
            <!-- View 2: Performance Chart -->
            <div class="view performance-chart">
              <canvas id="chart_${robot.robot}"></canvas>
            </div>
            <!-- View 3: Scoring Breakdown -->
            <div class="view scoring-breakdown">
          <h3>Scoring Table</h3>
              <table class="scoring-table">
                <thead>
                  <tr>
                    <th>Scoring Location</th>
                    <th>Successes</th>
                    <th>Attempts</th>
                    <th>Rate</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Level 1</td>
                    <td>${robot.count_level_1 || 0}</td>
                    <td>${robot.level1_attempts || 0}</td>
                    <td>${(robot.level1_attempts > 0) ? ((robot.count_level_1/robot.level1_attempts)*100).toFixed(1)+"%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 2</td>
                    <td>${robot.count_level_2 || 0}</td>
                    <td>${robot.level2_attempts || 0}</td>
                    <td>${(robot.level2_attempts > 0) ? ((robot.count_level_2/robot.level2_attempts)*100).toFixed(1)+"%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 3</td>
                    <td>${robot.count_level_3 || 0}</td>
                    <td>${robot.level3_attempts || 0}</td>
                    <td>${(robot.level3_attempts > 0) ? ((robot.count_level_3/robot.level3_attempts)*100).toFixed(1)+"%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 4</td>
                    <td>${robot.count_level_4 || 0}</td>
                    <td>${robot.level4_attempts || 0}</td>
                    <td>${(robot.level4_attempts > 0) ? ((robot.count_level_4/robot.level4_attempts)*100).toFixed(1)+"%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Algae Net</td>
                    <td>${robot.algae_net_success || 0}</td>
                    <td>${robot.algae_net_attempts || 0}</td>
                    <td>${(robot.algae_net_attempts > 0) ? ((robot.algae_net_success/robot.algae_net_attempts)*100).toFixed(1)+"%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Algae Proc</td>
                    <td>${robot.algae_processor_success || 0}</td>
                    <td>${robot.algae_processor_attempts || 0}</td>
                    <td>${(robot.algae_processor_attempts > 0) ? ((robot.algae_processor_success/robot.algae_processor_attempts)*100).toFixed(1)+"%" : "0%"}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        `;
      });

      // Render the built HTML into the robot container
      container.innerHTML = html;
      
      // For each robot, create a performance chart using Chart.js
      robots.forEach(robot => {
        const canvas = document.getElementById("chart_" + robot.robot);
        if (!canvas) return;
        const ctx = canvas.getContext("2d");
        const offense = Number(robot.offense_score) || 0;
        const defense = Number(robot.defense_score) || 0;
        const auton = Number(robot.auton_score) || 0;
        const coop = Number(robot.cooperative_score) || 0;
        const coopColor = coop >= 0 ? "rgba(0, 128, 0, 0.6)" : "rgba(243,53,53,0.6)";
        const coopBorderColor = coop >= 0 ? "rgba(0, 128, 0, 1)" : "rgba(243,53,53,1)";
        const chartData = {
          labels: ["Offense", "Defense", "Auton", "Co-op"],
          datasets: [{
            label: "Performance",
            data: [offense, defense, auton, coop],
            backgroundColor: [
              "rgba(20, 61, 96, 0.6)",
              "rgba(39, 102, 123, 0.6)",
              "rgba(160, 200, 120, 0.6)",
              coopColor
            ],
            borderColor: [
              "rgb(20, 61, 96)",
              "rgb(39, 102, 123)",
              "rgb(160, 200, 120)",
              coopBorderColor
            ],
            borderWidth: 1
          }]
        };
        // Create a new bar chart on the canvas
        new Chart(ctx, {
          type: "bar",
          data: chartData,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { beginAtZero: true },
              y: { beginAtZero: true }
            },
            plugins: { legend: { display: false } }
          }
        });
      });
      
      // After building all cards, start the automatic cycling of views on each card
      cycleRobotCardViews();
    }
    
    // -----------------------------
    // CYCLE ROBOT CARD VIEWS FUNCTION
    // -----------------------------
    // Automatically cycles through the different views (stat cards, chart, table) within each robot card.
    function cycleRobotCardViews() {
      const robotCards = document.querySelectorAll(".robot-card");
      robotCards.forEach(card => {
        const views = card.querySelectorAll(".view");
        // If there is only one view, no need to cycle
        if (views.length < 2) return;
        
        // Initialize each view's position and opacity
        views.forEach((view, idx) => {
          view.style.position = "absolute";
          view.style.top = "0rem"; // leave room for the top row
          view.style.left = "0";
          view.style.width = "100%";
          view.style.height = "calc(100% - 0rem)";
          view.style.transition = "transform 0.5s ease, opacity 0.5s ease";
          // First view is visible; others start off-screen to the right
          view.style.transform = (idx === 0) ? "translateX(0)" : "translateX(100%)";
          view.style.opacity = (idx === 0) ? "1" : "0";
        });
        
        let currentIndex = 0;
        // Define a cycle function to change views automatically
        function cycle() {
          // Animate current view sliding out to the left
          views[currentIndex].style.transform = "translateX(-100%)";
          views[currentIndex].style.opacity = "0";
          
          // Update index to next view
          currentIndex = (currentIndex + 1) % views.length;
          
          // Animate the next view sliding in from the right
          views[currentIndex].style.transform = "translateX(0)";
          views[currentIndex].style.opacity = "1";
          
          // After transition, reset the previous view off-screen to the right
          setTimeout(() => {
            let prevIndex = (currentIndex - 1 + views.length) % views.length;
            views[prevIndex].style.transform = "translateX(100%)";
          }, 500); // 0.5s matches the CSS transition duration
          
          // Set a random delay (between 4 and 10 seconds) before the next cycle
          const randomDelay = 4000 + Math.random() * 6000;
          setTimeout(cycle, randomDelay);
        }
        
        // Start the cycle with an initial random delay (between 6 and 14 seconds)
        const initialDelay = 6000 + Math.random() * 8000;
        setTimeout(cycle, initialDelay);
      });
    }
    
    // -----------------------------
    // FETCH PREDICTION FUNCTION
    // -----------------------------
    // This function fetches match prediction data based on current robot data.
    function fetchPrediction() {
      // Retrieve the event and match selection values
      const eventName = document.getElementById("eventDropdown").value;
      const matchNumber = document.getElementById("matchDropdown").value;
      let blueAlliance = [];
      let redAlliance = [];
      
      // Loop through each robot to classify by alliance
      fetchedRobots.forEach(robot => {
        if (robot.alliance && robot.alliance.toLowerCase() === "blue") {
          blueAlliance.push(robot.robot.toString().trim());
        } else if (robot.alliance && robot.alliance.toLowerCase() === "red") {
          redAlliance.push(robot.robot.toString().trim());
        }
      });
      let hist_weight = 0.5;
      
      // Build the API URL with query parameters
      const apiUrl = `predict.php?event_name=${encodeURIComponent(eventName)}&match_no=${encodeURIComponent(matchNumber)}&blue_alliance=${encodeURIComponent(blueAlliance.join(','))}&red_alliance=${encodeURIComponent(redAlliance.join(','))}&hist_weight=${encodeURIComponent(hist_weight)}`;
      const xhr = new XMLHttpRequest();
      xhr.open("GET", apiUrl, true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          try {
            let data = JSON.parse(xhr.responseText);
            if (data.error) {
              document.getElementById("predictionResult").innerHTML = `<span style="color:red;">${data.error}</span>`;
              return;
            }
            aggregatedData = data;
            updatePredictionDisplay();
          } catch (error) {
            document.getElementById("predictionResult").innerHTML = `<span style="color:red;">Error processing prediction data.</span>`;
          }
        }
      };
      xhr.send();
    }
    
    // -----------------------------
    // UPDATE PREDICTION DISPLAY FUNCTION
    // -----------------------------
    // (This function is commented out in the code; uncomment and modify as needed.)
    // function updatePredictionDisplay() {
    //   let headerHtml = `<strong>Event:</strong> ${aggregatedData.event_name}<br>`;
    //   headerHtml += `<strong>Match No:</strong> ${aggregatedData.match_no}<br>`;
    //   headerHtml += `<strong>Blue Alliance Score:</strong> ${aggregatedData.blue_score}<br>`;
    //   headerHtml += `<strong>Red Alliance Score:</strong> ${aggregatedData.red_score}<br>`;
    //   headerHtml += `<strong>Predicted Winner:</strong> ${aggregatedData.predicted_winner}<br>`;
    //   // Update the prediction result container with headerHtml
    //   document.getElementById("predictionResult").innerHTML = headerHtml;
    //   
    //   // Change prediction card background color based on predicted winner
    //   const card = document.getElementById("predictionCard");
    //   if (aggregatedData.predicted_winner.toLowerCase().includes("blue")) {
    //     card.style.backgroundColor = "#cce5ff"; // light blue
    //   } else if (aggregatedData.predicted_winner.toLowerCase().includes("red")) {
    //     card.style.backgroundColor = "#f8d7da"; // light red
    //   } else {
    //     card.style.backgroundColor = "#e2e3e5"; // light gray for tie/other
    //   }
    // }

    // -----------------------------
    // POPULATE ALL ROBOT DROPDOWNS FUNCTION
    // -----------------------------
    // Populates the dropdown menus for robot selections with unique robot identifiers.
    function populateAllRobotDropdowns() {
      // Extract unique robot IDs from fetchedRobots
      const uniqueRobots = [...new Set(fetchedRobots.map(r => r.robot))];
      // Define the dropdown IDs to populate
      const dropdownIds = ["red1", "red2", "red3", "blue1", "blue2", "blue3"];
      
      // Loop through each dropdown and add options
      dropdownIds.forEach(id => {
        const dropdown = document.getElementById(id);
        if (dropdown) {
          // Clear existing options and add a default placeholder
          dropdown.innerHTML = "<option value=''>-- Select Robot --</option>";
          uniqueRobots.forEach(robot => {
            let option = document.createElement("option");
            option.value = robot;
            option.textContent = robot;
            dropdown.appendChild(option);
          });
        }
      });
    }
    
    // -----------------------------
    // GET SELECTED ALLIANCE ROBOTS FUNCTION
    // -----------------------------
    // Retrieves the selected robot values from alliance dropdowns.
    function getSelectedAllianceRobots() {
      // Define arrays of dropdown IDs for each alliance
      const blueIds = ["blue1", "blue2", "blue3"];
      const redIds = ["red1", "red2", "red3"];
      
      // Map over dropdown IDs and extract non-empty selections
      let blueAlliance = blueIds.map(id => {
        const dd = document.getElementById(id);
        return dd ? dd.value : "";
      }).filter(val => val !== "");

      let redAlliance = redIds.map(id => {
        const dd = document.getElementById(id);
        return dd ? dd.value : "";
      }).filter(val => val !== "");

      return { blueAlliance, redAlliance };
    }

    // -----------------------------
    // CHECK IF ALL DROPDOWNS ARE FILLED FUNCTION
    // -----------------------------
    // Checks that all alliance dropdowns have a selected value before sending a prediction request.
    function checkAllDropdownsFilled() {
      const ids = ["blue1", "blue2", "blue3", "red1", "red2", "red3"];
      // Verify that every dropdown has a non-empty trimmed value
      const allFilled = ids.every(id => {
        const el = document.getElementById(id);
        return el && el.value.trim() !== "";
      });
      if (allFilled) {
        sendPredictionRequest();
      }
    }

    // Add event listeners to the alliance dropdowns to trigger check on change
    ["blue1", "blue2", "blue3", "red1", "red2", "red3"].forEach(id => {
      const dropdown = document.getElementById(id);
      if (dropdown) {
        dropdown.addEventListener("change", checkAllDropdownsFilled);
      }
    });

    // -----------------------------
    // SEND PREDICTION REQUEST FUNCTION
    // -----------------------------
    // Builds the API URL and sends a prediction request based on the selected robots.
    function sendPredictionRequest() {
      // Show a loading animation in the prediction card
      showPredictionLoading();

      // Retrieve event data from PHP
      const eventData = <?php echo json_encode($events); ?>;
      const currentEvent = eventData[0];
      const eventName = currentEvent.event_name;
      const matchNumber = 1313; // Fixed match number for this prediction
      const hist_weight = 0.5;

      // Get the selected alliance robot values from dropdowns
      const blueAlliance = ["blue1", "blue2", "blue3"].map(id => document.getElementById(id).value);
      const redAlliance = ["red1", "red2", "red3"].map(id => document.getElementById(id).value);

      // Build the API URL with query parameters
      const apiUrl = `predict.php?event_name=${encodeURIComponent(eventName)}&match_no=${encodeURIComponent(matchNumber)}&blue_alliance=${encodeURIComponent(blueAlliance.join(','))}&red_alliance=${encodeURIComponent(redAlliance.join(','))}&hist_weight=${encodeURIComponent(hist_weight)}`;

      // Use fetch to request prediction data
      fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
          console.log("Prediction Data:", data);
          // Store the returned prediction data globally
          aggregatedData = data;
          
          // Process the prediction data to create tables for each alliance
          let blueStats = [];
          let redStats = [];
          let blueContributions = [];
          let redContributions = [];

          if (aggregatedData.blue_stats && Array.isArray(aggregatedData.blue_stats)) {
            blueStats = aggregatedData.blue_stats;
          }
          if (aggregatedData.red_stats && Array.isArray(aggregatedData.red_stats)) {
            redStats = aggregatedData.red_stats;
          }
          if (aggregatedData.blue_contributions && Array.isArray(aggregatedData.blue_contributions)) {
            blueContributions = aggregatedData.blue_contributions;
          }
          if (aggregatedData.red_contributions && Array.isArray(aggregatedData.red_contributions)) {
            redContributions = aggregatedData.red_contributions;
          }

          // Build HTML content for the prediction card
          let headerHtml = `<strong><p>Blue Alliance Score:</strong> ${aggregatedData.blue_score}<br>`;
          headerHtml += '<table><th>Robot</th><th>Points</th>';
          blueContributions.forEach(stat => {
            headerHtml += `<tr><td>${stat.robot}</td><td> ${Number(stat.predicted_ppm).toFixed(2)}</td></tr>`;
          });
          headerHtml += '</table>';
          headerHtml += `<strong>Red Alliance Score:</strong> ${aggregatedData.red_score}<br>`;
          headerHtml += '<table><th>Robot</th><th>Points</th>';
          redContributions.forEach(stat => {
            headerHtml += `<tr><td>${stat.robot}</td><td> ${Number(stat.predicted_ppm).toFixed(2)}</td></tr>`;
          });
          headerHtml += '</table>';
          headerHtml += `<strong>Predicted Winner:</strong> ${aggregatedData.predicted_winner}<br><br>`;

          // Update the prediction card with the generated HTML
          const headerEl = document.getElementById("predictionCard");
          headerEl.innerHTML = headerHtml;

          // Adjust background color based on predicted winner
          if (aggregatedData.predicted_winner.toLowerCase().includes("blue")) {
            headerEl.style.backgroundColor = 'rgba(33,91,159,1)';
            headerEl.style.color = '#fff';
          } else if (aggregatedData.predicted_winner.toLowerCase().includes("red")) {
            headerEl.style.backgroundColor = 'rgba(96,20,55,1)';
            headerEl.style.color = '#fff';
          } else {
            headerEl.style.backgroundColor = "#e2e3e5"; // Light gray for tie or other outcomes
            headerEl.style.color = '#000';
          }
        })
        .catch(error => {
          console.error("Prediction fetch error:", error);
        });
    }

    // -----------------------------
    // INITIAL ROBOT SELECTION FUNCTION
    // -----------------------------
    // Automatically selects robot values for dropdowns based on event data.
    function selectRobots() {
      const eventData = <?php echo json_encode($events); ?>;
      if (!eventData || eventData.length === 0) {
        console.error("No event data available");
        return;
      }

      // Extract robot identifiers from event data
      const robots = eventData.map(row => row.robot);
      console.log("Robots:", robots);
      
      // Initially assign a value and dispatch a change event
      document.getElementById("red1").value = robots[1];
      document.getElementById("red1").dispatchEvent(new Event('change'));

      // Get references to all dropdown elements
      const red1 = document.getElementById("red1");
      const red2 = document.getElementById("red2");
      const red3 = document.getElementById("red3");
      const blue1 = document.getElementById("blue1");
      const blue2 = document.getElementById("blue2");
      const blue3 = document.getElementById("blue3");

      // Use a delay to ensure dropdowns are populated before assigning values
      setTimeout(() => {
        red1.value = robots[0];
        red2.value = robots[1];
        red3.value = robots[2];
        blue1.value = robots[3];
        blue2.value = robots[4];
        blue3.value = robots[5];

        // Dispatch change events so that any listeners update accordingly
        red1.dispatchEvent(new Event('change'));
        red2.dispatchEvent(new Event('change'));
        red3.dispatchEvent(new Event('change'));
        blue1.dispatchEvent(new Event('change'));
        blue2.dispatchEvent(new Event('change'));
        blue3.dispatchEvent(new Event('change'));
      }, 2000); // Delay set to 2000ms (adjust as needed)
    }

    // Call selectRobots to auto-fill the dropdowns on page load
    selectRobots();

    // -----------------------------
    // SHOW PREDICTION LOADING FUNCTION
    // -----------------------------
    // Displays a loading animation in the prediction card while data is being fetched.
    function showPredictionLoading() {
      const predictionContainer = document.getElementById("predictionCard");

      predictionContainer.style.display = 'block';
      predictionContainer.style.backgroundColor = '#222';

      // Clear previous content
      predictionContainer.innerHTML = '';
      
      // Add loader elements (HTML for two different loader animations)
      predictionContainer.innerHTML = `<div class="loader"> </div> <div class="loader2"> </div><div class="loader"> </div>`;
    }
    
    // -----------------------------
    // INITIAL DATA FETCH
    // -----------------------------
    // Start by fetching the robot cards when the page loads.
    fetchRobotCards();
  </script>
</body>
</html>
