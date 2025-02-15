<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FRC Match Viewer with Robot Filter</title>
  <style>
    /* Global Fonts & Base Styles */
    @font-face {
      font-family: 'Roboto';
      src: url('/../scouting/fonts/roboto/Roboto-Regular.ttf') format('ttf');
      font-weight: normal;
      font-style: normal;
    }
    @font-face {
      font-family: 'Griffy';
      src: url('/../scouting/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
      font-weight: normal;
      font-style: normal;
    }
    @font-face {
      font-family: 'Comfortaa';
      src: url('/../scouting/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf');
      font-weight: normal;
      font-style: normal;
    }
    body, html {
      font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
    }
    /* Outer container */
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
    /* Grid layout for 2x2 dropdowns */
    .grid-container {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      grid-gap: 1rem;
      margin-bottom: 1rem;
    }
    .grid-item {
      display: flex;
      flex-direction: column;
    }
    .grid-item label {
      margin-bottom: 0.3rem;
      font-weight: bold;
      font-size: 1rem;
    }
    .grid-item select,
    .grid-item input {
      padding: 0.5rem;
      border-radius: 4px;
      border: 1px solid #555;
      background: #444;
      color: #eee;
      font-size: 1rem;
      width: 100%;
      box-sizing: border-box;
    }
    /* Robot Cards Layout */
    .robot-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      padding: 1rem;
      justify-content: center;
    }
    .card {
      background: #fff;
      color: #333;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      width: 100%;
      max-width: 700px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      margin-bottom: 1rem;
    }
    /* Top row: robot details (left) & chart (right) */
    .top-row {
      display: flex;
      flex-direction: row;
      gap: 1rem;
      padding: 1rem;
      align-items: flex-start;
    }
    .robot-details {
      flex: 1 1 auto;
    }
    .robot-chart {
      flex: 0 0 400px;
      height: 400px;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 0.5rem;
    }
    .robot-chart canvas {
      width: 100% !important;
      height: 100% !important;
      display: block;
    }
    /* Bottom row: scoring breakdown table (full width) */
    .scoring-breakdown {
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
    }
    .scoring-breakdown h4 {
      margin-top: 0;
    }
    .scoring-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 0.5rem;
      font-size: 0.9rem;
    }
    .scoring-table th, .scoring-table td {
      border: 1px solid #ccc;
      padding: 0.5rem;
      text-align: center;
    }
    .scoring-table th {
      background: #f0f0f0;
      color: #333;
    }
    /* Hide the filter list (if using it) */
    #robotFilterList {
      display: none;
    }
    .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
    }
  </style>
  <!-- Include Chart.js -->
  <script src="../js/Chart.bundle.js"></script>
  <script>
    /******************************************
     * Step 2: Client-Side Data Fetch & Caching
     ******************************************/
    // Helper: fetch with timeout
    function fetchWithTimeout(resource, options = {}) {
      const { timeout = 5000 } = options;
      const controller = new AbortController();
      const id = setTimeout(() => controller.abort(), timeout);
      return fetch(resource, { ...options, signal: controller.signal })
        .finally(() => clearTimeout(id));
    }

    // Fetch updated data from the server endpoint (update_data.php)
    // and store it in localStorage.
    function updateData() {
      return fetchWithTimeout('update_data.php', { timeout: 5000 })
        .then(response => {
          if (!response.ok) throw new Error("Network response was not ok");
          return response.json();
        })
        .then(data => {
          // Save the fresh data to localStorage
          localStorage.setItem('updateData', JSON.stringify(data));
          // Update UI by populating the event dropdown
          populateEventDropdown();
          return data;
        })
        .catch(error => {
          console.warn("Could not fetch fresh data, using cached data.", error);
          const cached = localStorage.getItem('updateData');
          if (cached) {
            const data = JSON.parse(cached);
            populateEventDropdown();
            return data;
          } else {
            console.error("No cached data available.");
            throw error;
          }
        });
    }

    // Step 3: Populate the Event Dropdown from Cached JSON
    function populateEventDropdown() {
      const storedData = localStorage.getItem('updateData');
      if (!storedData) return; // No data available
      const data = JSON.parse(storedData);
      const events = data.events; // Assumes update_data.php returns an "events" array
      const eventDropdown = document.getElementById("eventDropdown");
      eventDropdown.innerHTML = "<option value=''>-- Select Event --</option>";
      events.forEach(e => {
        const option = document.createElement("option");
        option.value = e.event_name;
        option.textContent = e.event_name;
        eventDropdown.appendChild(option);
      });
    }

    // Additional functions (fetchMatches, fetchRobots, populateRobotToggleDropdown,
    // toggleRobotFilter, updateRobotCards, displayRobotCards) remain as before.
    // They will use the globally stored data (from localStorage) when needed.

    /******************************************
     * Existing Data Fetching & UI Functions
     ******************************************/
    let fetchedRobots = [];  // Global variable for robot data
    let filterRobots = [];   // Global variable for exclusion filter

   function fetchMatches() {
  const eventName = document.getElementById("eventDropdown").value;
  const matchDropdown = document.getElementById("matchDropdown");
  const robotContainer = document.getElementById("robotContainer");

  matchDropdown.innerHTML = "<option value=''>-- Select Match --</option><option value='all'>All Matches</option>";
  robotContainer.innerHTML = "";

  if (!eventName) return;

  // Check if online first
  if (!navigator.onLine) {
    // If offline, do nothing (or optionally use cached matches)
    console.warn("Offline – cannot update matches");
    return;
  }

  const xhr = new XMLHttpRequest();
  xhr.open("GET", "fetch_matches.php?event_name=" + encodeURIComponent(eventName), true);
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        let response = JSON.parse(xhr.responseText);
        if (response.error) {
          console.error(response.error);
          return;
        }
        if (response.length > 0) {
          response.forEach(match => {
            let option = document.createElement("option");
            option.value = match.match_number;
            option.textContent = "Match " + match.match_number;
            matchDropdown.appendChild(option);
          });
        } else {
          console.log("No matches found for this event.");
        }
      } else {
        console.warn("Failed to fetch new matches; using cached data if available.");
      }
    }
  };
  xhr.send();
}


    function fetchRobots() {
      const eventName = document.getElementById("eventDropdown").value;
      const matchNumber = document.getElementById("matchDropdown").value;
      const robotContainer = document.getElementById("robotContainer");
      robotContainer.innerHTML = "";
      if (!eventName || !matchNumber) return;

      const xhr = new XMLHttpRequest();
      console.log("Event:", eventName, "Match Number:", matchNumber);
      if (matchNumber !== 'all') {
        xhr.open("GET", "fetch_robot_data.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber), true);
      } else {
        xhr.open("GET", "fetch_robot_data2.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber), true);
      }
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          console.log("Fetch Robots Raw Response:", xhr.responseText);
          try {
            let data = JSON.parse(xhr.responseText);
            if (data.error) {
              console.error("Error:", data.error);
              robotContainer.innerHTML = `<p style="color:red;">${data.error}</p>`;
              return;
            }
            let robotsArray;
            if (Array.isArray(data)) {
              robotsArray = data;
            } else if (data.robots && Array.isArray(data.robots)) {
              robotsArray = data.robots;
            } else {
              robotsArray = [data];
            }
            fetchedRobots = robotsArray;
            filterRobots = [];
            updateRobotCards();
            populateRobotToggleDropdown();
          } catch (error) {
            console.error("JSON Parsing Error:", error);
            console.log("Response:", xhr.responseText);
            robotContainer.innerHTML = `<p style="color:red;">Error processing data. Check console.</p>`;
          }
        }
      };
      xhr.send();
    }

    function populateRobotToggleDropdown() {
      const dropdown = document.getElementById("robotToggleDropdown");
      dropdown.innerHTML = "<option value=''>-- Select Robot --</option>";
      const uniqueRobots = [...new Set(fetchedRobots.map(r => r.robot))];
      uniqueRobots.forEach(robotNum => {
        const option = document.createElement("option");
        option.value = robotNum;
        option.textContent = robotNum;
        dropdown.appendChild(option);
      });
    }

    function toggleRobotFilter() {
      const dropdown = document.getElementById("robotToggleDropdown");
      const selectedNum = parseInt(dropdown.value, 10);
      if (!selectedNum) return;
      const idx = filterRobots.indexOf(selectedNum);
      if (idx === -1) {
        filterRobots.push(selectedNum);
      } else {
        filterRobots.splice(idx, 1);
      }
      updateRobotCards();
      dropdown.value = "";
    }

    function updateRobotCards() {
      const sortBy = document.getElementById("sortOption").value;
      let sorted = [...fetchedRobots];
      if (sortBy === "alliance") {
        sorted.sort((a, b) => (a.alliance || "Unknown").localeCompare(b.alliance || "Unknown"));
      } else {
        sorted.sort((a, b) => (b[sortBy] || 0) - (a[sortBy] || 0));
      }
      const finalList = sorted.filter(robot => !filterRobots.includes(parseInt(robot.robot, 10)));
      displayRobotCards(finalList);
    }

    function displayRobotCards(robots) {
      const container = document.getElementById("robotContainer");
      if (!container) {
        console.error("robotContainer element not found.");
        return;
      }
      container.innerHTML = "";
      let html = "";
      robots.forEach((robot, index) => {
        html += `
          <div class="robot-card card">
            <!-- TOP ROW: text on left, chart on right -->
            <div class="top-row">
              <div class="robot-details">
                <h3>Robot ${robot.robot}</h3>
                <p><strong>Alliance:</strong> ${robot.alliance}</p>
                <p>Matches Played: ${robot.match_count}</p>
                <p>Top Scoring Location: ${robot.top_scoring_location || "N/A"}</p>
                <p>Offense Score: ${robot.offense_score}</p>
                <p>Defense Score: ${robot.defense_score}</p>
                <p>Auton Score: ${robot.auton_score}</p>
                <p>Cooperative Score: ${parseFloat(robot.cooperative_score).toFixed(2)}</p>
              </div>
              <div class="robot-chart">
                <canvas id="chart-${index}"></canvas>
              </div>
            </div>
            <!-- BOTTOM ROW: full-width scoring breakdown table -->
            <div class="scoring-breakdown">
              <h4>Scoring Breakdown</h4>
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
                    <td>${(robot.level1_attempts > 0) ? ((robot.count_level_1 / robot.level1_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 2</td>
                    <td>${robot.count_level_2 || 0}</td>
                    <td>${robot.level2_attempts || 0}</td>
                    <td>${(robot.level2_attempts > 0) ? ((robot.count_level_2 / robot.level2_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 3</td>
                    <td>${robot.count_level_3 || 0}</td>
                    <td>${robot.level3_attempts || 0}</td>
                    <td>${(robot.level3_attempts > 0) ? ((robot.count_level_3 / robot.level3_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Level 4</td>
                    <td>${robot.count_level_4 || 0}</td>
                    <td>${robot.level4_attempts || 0}</td>
                    <td>${(robot.level4_attempts > 0) ? ((robot.count_level_4 / robot.level4_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Algae Net</td>
                    <td>${robot.algae_net_success || 0}</td>
                    <td>${robot.algae_net_attempts || 0}</td>
                    <td>${(robot.algae_net_attempts > 0) ? ((robot.algae_net_success / robot.algae_net_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                  <tr>
                    <td>Algae Proc</td>
                    <td>${robot.algae_processor_success || 0}</td>
                    <td>${robot.algae_processor_attempts || 0}</td>
                    <td>${(robot.algae_processor_attempts > 0) ? ((robot.algae_processor_success / robot.algae_processor_attempts) * 100).toFixed(1) + "%" : "0%"}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;

      // Create the bar chart for each robot card.
      robots.forEach((robot, index) => {
        const canvas = document.getElementById(`chart-${index}`);
        if (!canvas) {
          console.error(`Canvas chart-${index} not found.`);
          return;
        }
        const ctx = canvas.getContext("2d");
        const offense = Number(robot.offense_score) || 0;
        const defense = Number(robot.defense_score) || 0;
        const auton = Number(robot.auton_score) || 0;
        const coop = Number(robot.cooperative_score) || 0;
        const coopColor = coop >= 0 ? "rgba(0, 128, 0, 0.6)" : "rgba(255, 0, 0, 0.6)";
        const coopBorderColor = coop >= 0 ? "rgba(0, 128, 0, 1)" : "rgba(255, 0, 0, 1)";
        const data = {
          labels: ["Offense", "Defense", "Auton", "Co-op"],
          datasets: [{
            label: "Performance",
            data: [offense, defense, auton, coop],
            backgroundColor: [
              "rgba(75, 192, 192, 0.6)",
              "rgba(153, 102, 255, 0.6)",
              "rgba(255, 205, 86, 0.6)",
              coopColor
            ],
            borderColor: [
              "rgba(75, 192, 192, 1)",
              "rgba(153, 102, 255, 1)",
              "rgba(255, 205, 86, 1)",
              coopBorderColor
            ],
            borderWidth: 1
          }]
        };
        new Chart(ctx, {
          type: "bar",
          data: data,
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
    }

    /******************************************
     * Load Data & Populate Event Dropdown (Step 2 & 3)
     ******************************************/
    window.addEventListener("load", function() {
      updateData().then(() => {
        populateEventDropdown();
      }).catch(() => {
        populateEventDropdown();
      });
    });
  </script>
</head>
<body>
  <div class="containerOuter">
    <div class="container">
      <img src="../images/statgoblinlogo.webp" class="logo" alt="Logo">
      <!-- 2x2 Grid for Dropdowns -->
      <div class="grid-container">
        <div class="grid-item">
          <label for="eventDropdown"><strong>Select an Event:</strong></label>
          <select id="eventDropdown" onchange="fetchMatches()">
            <option value="">-- Select Event --</option>
          </select>
        </div>
        <div class="grid-item">
          <label for="matchDropdown"><strong>Select a Match:</strong></label>
          <select id="matchDropdown" onchange="fetchRobots()">
            <option value="">-- Select Match --</option>
            <option value="all">All Matches</option>
          </select>
        </div>
        <div class="grid-item">
          <label for="sortOption"><strong>Sort by:</strong></label>
          <select id="sortOption" onchange="updateRobotCards()">
            <option value="alliance" selected>Alliance</option>
            <option value="offense_score">Offense Score</option>
            <option value="defense_score">Defense Score</option>
            <option value="cooperative_score">Cooperative Score</option>
          </select>
        </div>
        <div class="grid-item">
          <label for="robotToggleDropdown"><strong>Robot (Exclude):</strong></label>
          <select id="robotToggleDropdown" onchange="toggleRobotFilter()">
            <option value="">-- Select Robot --</option>
          </select>
        </div>
      </div>
      <!-- Hidden filter list -->
      <input type="text" id="robotFilterList" readonly placeholder="Filter list" />
    </div>
  </div>
  <!-- Robot Cards Container -->
  <div id="robotContainer" class="robot-cards"></div>
</body>
</html>
