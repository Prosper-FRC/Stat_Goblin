<?php
require_once '../php/database_connection.php';
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch unique events from scouting_submissions
$event_query = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name ASC");
$events = $event_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FRC Match Dashboard</title>
  <style>
    /* --- Font Faces --- */
    @font-face {
      font-family: 'Roboto';
      src: url('/../scouting/fonts/roboto/Roboto-Regular.ttf') format('ttf');
    }
    @font-face {
      font-family: 'Griffy';
      src: url('/../scouting/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
    }
    @font-face {
      font-family: 'Comfortaa';
      src: url('/../scouting/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf');
    }
    /* --- Global Styles --- */
    body, html {
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      font-family: 'Comfortaa', sans-serif;
      font-size: 16px;
      line-height: 1.5;
    }
    .container {
 
      margin: auto;
      padding: 1rem;
    }
    /* --- Header Card: Logo & Dropdowns --- */
.header-card {
width:100vw;
min-height:4rem;
  padding: 1rem;
 
  box-sizing: border-box;
  background: #333;
  color: #fff;
  border-radius: 8px;
  /* etc. */
}


.logoCard{background-color: #222 !important}
   .logo {
      width: 16rem;
      margin-left: auto;
    }
    .dropdowns {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 1rem;
      width: 100%;
    }
    .dropdowns select {
   
      font-size: .8rem;
      border: 1px solid #555;
      border-radius: 4px;
      background: #444;
      color: #eee;
    }

    /* We'll later set its background color based on predicted winner. */
    /* --- Robot Cards Container (Grid) --- */
    .robot-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, 16rem);
  gap: 1rem;
  justify-content: center;
    }

    .cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, 16rem);
  gap: 1rem;
  justify-content: center;
}

    /* --- Each Robot Card --- */
    .robot-card {
      width: 16rem;
      height: 16rem;
      background: #fff;
      color: #333;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      position: relative;
      overflow: hidden;
      /* Random rotation will be applied inline */
    }
    /* --- Top Row (robot name and alliance) --- */
    .robot-card .top-row {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      padding: 0.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: rgba(255,255,255,0.9);
      z-index: 2;
      box-sizing: border-box;
    }
    .robot-card .robot-name, .robot-card .alliance {
      font-weight: bold;
      font-size: 1.2rem;
    }
    /* --- Views Container --- */
    .robot-card .view {
      position: absolute;
      top: 3rem; /* leave space for the top row */
      left: 0;
      width: 100%;
      height: calc(100% - 3rem);
      transition: transform 0.5s ease, opacity 0.5s ease;
      opacity: 0;
      z-index: 1;
    }
    .robot-card .view.active {
      transform: translateX(0);
      opacity: 1;
    }
    .robot-card .view.inactive {
      transform: translateX(100%);
      opacity: 0;
    }
    /* --- Stat Cards (View 1) in a grid (2 columns) --- */
    .stat-cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem;
      padding: 0.5rem;
      box-sizing: border-box;
      height: 100%;
    }
    .stat-card {
      width: 100%;
      height: 3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid;
      border-radius: 4px;
      font-size: 0.9rem;
      text-align: center;
      box-sizing: border-box;
    }
    /* Stat card colors (order: Matches, Location, Offense, Defense, Auton, Co-op) */
    .stat-card.matches { background-color: rgba(20,96,61,0.6); border-color: rgb(20,96,61); }
    .stat-card.location { background-color: rgba(20,55,96,0.6); border-color: rgb(20,55,96); }
    .stat-card.offense { background-color: rgba(96,20,55,0.6); border-color: rgb(96,20,55); }
    .stat-card.defense { background-color: rgba(96,61,20,0.6); border-color: rgb(96,61,20); }
    .stat-card.auton { background-color: rgba(96,23,20,0.6); border-color: rgb(96,23,20); }
    .stat-card.coop { background-color: rgba(33,91,159,0.6); border-color: rgb(33,91,159); }
    /* --- Performance Chart (View 2) --- */
    .performance-chart {
      padding: 0.5rem;
      box-sizing: border-box;
      height: 100%;
    }
    .performance-chart canvas {
      width: 100% !important;
      height: 100% !important;
    }
    /* --- Scoring Breakdown Table (View 3) --- */
    .scoring-breakdown {
      padding: 0.5rem;
      box-sizing: border-box;
      overflow-y: auto;
      height: 100%;
    }
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
  </style>
</head>
<body>
  <div class="containerOuter">
  <div class="container">
    <!-- Header Card -->
    <div class="card header-card">
      <div class="dropdowns">
        <div>
          <label for="sortOption"><strong>Sort by:</strong></label><br>
          <select id="sortOption" onchange="updateRobotCards()">
  <option value="alliance" selected>Alliance</option>
  <option value="offense_score">Offense Score</option>
  <option value="defense_score">Defense Score</option>
  <option value="cooperative_score">Cooperative Score</option>
  <option value="robot">Robot</option> <!-- New sort option -->
</select>
        </div>
      </div>
    </div>
    <!-- Robot Cards Container -->
    <div id="robotContainer" class="robot-cards">
      <div class="robot-card logoCard" id="card_1">
        <img class="logo" src="../images/statgoblinlogo.webp" alt="Logo">
      </div>
    </div>
  </div>
</div>
  
  <script src="../js/Chart.bundle.js"></script>
  <script>
    // Global variables
    let fetchedRobots = [];
    let aggregatedData = {};
    let filterRobots = [];
    
    // Fetch matches for a selected event
    function fetchMatches() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchDropdown = document.getElementById("matchDropdown");
      const container = document.getElementById("robotContainer");
      matchDropdown.innerHTML = "<option value=''>-- Select Match --</option><option value='all'>All Matches</option>";
      container.innerHTML = "";
      if (!eventName) return;
      const xhr = new XMLHttpRequest();
      xhr.open("GET", "fetch_matches.php?event_name=" + encodeURIComponent(eventName), true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          let response = JSON.parse(xhr.responseText);
          if (response.error) { console.error(response.error); return; }
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
    
    // Fetch robot card data
    function fetchRobotCards() {

      const eventName ='battle of Cybertron';
      const matchNumber = 'all';

      const container = document.getElementById("robotContainer");
      container.innerHTML = "";
      if (!eventName || !matchNumber) return;
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
            let robotsArray = Array.isArray(data) ? data : (data.robots && Array.isArray(data.robots)) ? data.robots : [data];
            fetchedRobots = robotsArray;
            filterRobots = [];
            updateRobotCards();
      
          } catch (error) {
            console.error("JSON Parsing Error:", error);
            container.innerHTML = `<p style="color:red;">Error processing robot data.</p>`;
          }
        }
      };
      xhr.send();
    }
    

    
function updateRobotCards() {
  const sortBy = document.getElementById("sortOption").value;
  let sorted = [...fetchedRobots];

  if (sortBy === "robot") {
    // Ensure both values are strings for proper comparison
    sorted.sort((a, b) => String(a.robot).localeCompare(String(b.robot)));
  } else if (sortBy === "alliance") {
    sorted.sort((a, b) => (a.alliance || "Unknown").localeCompare(b.alliance || "Unknown"));
  } else {
    sorted.sort((a, b) => (b[sortBy] || 0) - (a[sortBy] || 0));
  }

  // Filter out any robot IDs that are in filterRobots.
  const finalList = sorted.filter(robot => !filterRobots.includes(parseInt(robot.robot, 10)));
  displayRobotCards(finalList);
}


    
    // Helper: Format location (replace underscores, capitalize first letter)
    function formatLocation(str) {
      if (!str) return "N/A";
      str = str.replace(/_/g, " ");
      return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    function displayRobotCards(robots) {
      const container = document.getElementById("robotContainer");
      if (!container) return;
  let html = `
<div class="robot-card logoCard" id="card_2">
  <img class="logo" src="../images/owlanalytics.png" alt="Logo">
</div>
<div id="predictionCard" class="card robot-card prediction-card">
  <div id="predictionHeader">
    <h3>Match Prediction</h3>
    <p id="predictionResult">Waiting for prediction...</p>
  </div>
</div>
 `;

  robots.forEach(robot => {
    html += `
          <div class="robot-card" >
            <div class="top-row">
              <div class="robot-name">Robot ${robot.robot}</div>
       
            </div>
            <!-- View 1: Stat Cards -->
            <div class="view stat-cards active">
              <div class="stat-card matches">Matches: ${robot.match_count || "N/A"}</div>
              <div class="stat-card location">Location: ${formatLocation(robot.top_scoring_location)}</div>
              <div class="stat-card offense">Offense: ${robot.offense_score || 0}</div>
              <div class="stat-card defense">Defense: ${robot.defense_score || 0}</div>
              <div class="stat-card auton">Auton: ${robot.auton_score || 0}</div>
              <div class="stat-card coop">Co-op: ${parseFloat(robot.cooperative_score || 0).toFixed(2)}</div>
            </div>
            <!-- View 2: Performance Chart -->
            <div class="view performance-chart">
              <canvas id="chart_${robot.robot}"></canvas>
            </div>
            <!-- View 3: Scoring Breakdown -->
            <div class="view scoring-breakdown">
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
      container.innerHTML = html;
      
      // Create performance charts for each robot.
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
      
      // Cycle through views with sliding effect:
      cycleRobotCardViews();
    }
    
function cycleRobotCardViews() {
  const robotCards = document.querySelectorAll(".robot-card");
  robotCards.forEach(card => {
    const views = card.querySelectorAll(".view");
    if (views.length < 2) return;
    
    // Set up initial view positions
    views.forEach((view, idx) => {
      view.style.position = "absolute";
      view.style.top = "3rem"; // leave room for top row
      view.style.left = "0";
      view.style.width = "100%";
      view.style.height = "calc(100% - 3rem)";
      view.style.transition = "transform 0.5s ease, opacity 0.5s ease";
      view.style.transform = (idx === 0) ? "translateX(0)" : "translateX(100%)";
      view.style.opacity = (idx === 0) ? "1" : "0";
    });
    
    let currentIndex = 0;
    function cycle() {
      // Slide current view out to left and fade out.
      views[currentIndex].style.transform = "translateX(-100%)";
      views[currentIndex].style.opacity = "0";
      
      // Determine next view index.
      currentIndex = (currentIndex + 1) % views.length;
      
      // Slide next view in from right and fade in.
      views[currentIndex].style.transform = "translateX(0)";
      views[currentIndex].style.opacity = "1";
      
      // After the transition, reset the previous view off-screen to right.
      setTimeout(() => {
        let prevIndex = (currentIndex - 1 + views.length) % views.length;
        views[prevIndex].style.transform = "translateX(100%)";
      }, 500); // 0.5s matches the CSS transition
      
      // Set a random delay between 2000 and 5000ms before cycling again.
      const randomDelay = 4000 + Math.random() * 6000;
      setTimeout(cycle, randomDelay);
    }
    
    // Start the cycle with a random initial delay.
    const initialDelay = 6000 + Math.random() * 8000;
    setTimeout(cycle, initialDelay);
  });
}

    

    
    // For prediction, here we assume a separate fetchPrediction() function exists.
    // (The prediction functionality is not fully detailed here, but you can
    // adapt your existing predict.php logic.)
    function fetchPrediction() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchNumber = document.getElementById("matchDropdown").value;
      let blueAlliance = [];
      let redAlliance = [];
      fetchedRobots.forEach(robot => {
        if (robot.alliance && robot.alliance.toLowerCase() === "blue") {
          blueAlliance.push(robot.robot.toString().trim());
        } else if (robot.alliance && robot.alliance.toLowerCase() === "red") {
          redAlliance.push(robot.robot.toString().trim());
        }
      });
      let hist_weight = 0.5;
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
    
    // Update prediction display in the prediction card.
    function updatePredictionDisplay() {
      let headerHtml = `<strong>Event:</strong> ${aggregatedData.event_name}<br>`;
      headerHtml += `<strong>Match No:</strong> ${aggregatedData.match_no}<br>`;
      headerHtml += `<strong>Blue Alliance Score:</strong> ${aggregatedData.blue_score}<br>`;
      headerHtml += `<strong>Red Alliance Score:</strong> ${aggregatedData.red_score}<br>`;
      headerHtml += `<strong>Predicted Winner:</strong> ${aggregatedData.predicted_winner}<br>`;
      // Update header content.
      document.getElementById("predictionResult").innerHTML = headerHtml;
      
      // Change prediction card background color based on winner.
      const card = document.getElementById("predictionCard");
      if (aggregatedData.predicted_winner.toLowerCase().includes("blue")) {
        card.style.backgroundColor = "#cce5ff"; // light blue
      } else if (aggregatedData.predicted_winner.toLowerCase().includes("red")) {
        card.style.backgroundColor = "#f8d7da"; // light red
      } else {
        card.style.backgroundColor = "#e2e3e5"; // light gray for tie/other
      }
    }


    fetchRobotCards();
  </script>
</body>
</html>
