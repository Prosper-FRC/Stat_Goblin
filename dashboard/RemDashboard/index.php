<?php
// Database connection details
$host = 'localhost';
$dbname = 'frc_scouting';
$username = 'root';
$password = 'pw123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get the data for ranking
    $query = "SELECT robot, SUM(points) AS total_points FROM scouting_submissions GROUP BY robot ORDER BY total_points DESC";
    $stmt = $pdo->query($query);
    $robots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get the data for the Google Line Chart (Points over Time)
    $query_time_series = "SELECT time_sec, SUM(points) AS points FROM scouting_submissions GROUP BY time_sec ORDER BY time_sec";
    $stmt_time_series = $pdo->query($query_time_series);
    $time_series = $stmt_time_series->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRC Scouting Dashboard</title>

    <!-- Load jQuery & Chart.js -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }
        h1, h2 {
            text-align: center;
            margin-top: 20px;
        }
        .container {
            margin: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        #chart-container {
            margin-top: 20px;
            text-align: center;
        }
        #chart-container canvas {
            max-width: 100%;
            height: 400px;
        }
    </style>
</head>
<body>

    <h1>FRC Scouting Dashboard</h1>

    <div class="container">
        <!-- Table of Robot Rankings -->
        <h2>Robot Rankings by Points</h2>
        <table>
            <tr>
                <th>Rank</th>
                <th>Robot</th>
                <th>Total Points</th>
            </tr>
            <?php
            $rank = 1;
            foreach ($robots as $robot) {
                echo "<tr>
                        <td>$rank</td>
                        <td>{$robot['robot']}</td>
                        <td>{$robot['total_points']}</td>
                      </tr>";
                $rank++;
            }
            ?>
        </table>

        <!-- Bar Chart for Robot Points -->
        <h2>Robot Points Chart</h2>
        <div class="chart-container">
            <canvas id="points_barCanvas"></canvas>
        </div>

        <!-- Line Chart for Points Over Time -->
        <h2>Points Over Time</h2>
        <div class="chart-container" style="width: 100%; height: 500px;">
            
             <canvas id="points_overTimeCanvas"></canvas>

        </div>
    </div>

        <!-- Radar Chart for Offensee -->
        <h2>Offense</h2>
        <div class="chart-container" style="width: 100%; height: 500px;">
            
             <canvas id="OffenseCanvas"></canvas>

        </div>
    </div>





    

    <script>
        $(document).ready(function () {
            console.log("Document ready. Calling showGraph()...");
            points_bar();
            point_over_time();
            offense_chart();
        });

        function points_bar() {
            console.log("Fetching data from points_bar.php...");
            $.post("charts/points_bar.php", function (data) {
                console.log("Data received:", data);
                if (!Array.isArray(data) || data.length === 0) {
                    console.log("No valid data for chart.");
                    return;
                }

                var robotNames = [];
                var totalPoints = [];
                var barColors = [];

                // Function to generate random colors
                function getRandomColor() {
                    return "rgb(" + (Math.floor(Math.random() * 255)) + "," +
                                    (Math.floor(Math.random() * 255)) + "," +
                                    (Math.floor(Math.random() * 255)) + ")";
                }

                for (var i in data) {
                    robotNames.push(data[i].robot);
                    totalPoints.push(data[i].total_points);
                    barColors.push(getRandomColor()); // Assign a unique color
                }

                var chartdata = {
                    labels: robotNames,
                    datasets: [
                        {
                            label: 'Total Points',
                            backgroundColor: barColors, // Use dynamic colors
                            borderColor: barColors, // Border color same as background
                            hoverBackgroundColor: '#CCCCCC',
                            hoverBorderColor: '#666666',
                            data: totalPoints
                        }
                    ]
                };

                var ctx = document.getElementById("points_barCanvas").getContext("2d");
                new Chart(ctx, { type: 'bar', data: chartdata });
            });
        }



        function point_over_time() {

        $(document).ready(function () {
    console.log("Fetching data from points_over_time.php...");
    $.post("charts/points_over_time.php", function (data) {
        console.log("Data received:", data);
        if (!Array.isArray(data) || data.length === 0) {
            console.log("No valid data for chart.");
            return;
        }

        var datasets = {};
        var matchTimes = new Set();

        // Function to generate random colors for each robot
        function getRandomColor() {
            return "rgb(" + (Math.floor(Math.random() * 255)) + "," +
                            (Math.floor(Math.random() * 255)) + "," +
                            (Math.floor(Math.random() * 255)) + ")";
        }

        // Organize data by robot
        data.forEach(entry => {
            let robot = entry.robot;
            let time = entry.time_sec;
            let points = entry.total_points;

            // Collect unique match times
            matchTimes.add(time);

            // Initialize robot dataset if not exists
            if (!datasets[robot]) {
                datasets[robot] = {
                    label: robot,
                    borderColor: getRandomColor(),
                    fill: false,
                    data: []
                };
            }

            // Push data point for this robot
            datasets[robot].data.push({ x: time, y: points });
        });

        // Convert match times to sorted array
        let sortedMatchTimes = Array.from(matchTimes).sort((a, b) => a - b);

        // Convert dataset object to array for Chart.js
        var chartDatasets = Object.values(datasets);

        var chartdata = {
            labels: sortedMatchTimes,
            datasets: chartDatasets
        };

        var ctx = document.getElementById("points_overTimeCanvas").getContext("2d");
        new Chart(ctx, {
            type: 'line',
            data: chartdata,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: "Match Time (Seconds)"
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: "Points"
                        }
                    }
                }
            }
        });
    });
});


        }





function offense_chart() {

    console.log("Fetching data from offense.php...");
    $.post("charts/offense.php", function (data) {
        console.log("Data received:", data);

        if (!Array.isArray(data) || data.length === 0) {
            console.log("No valid data for chart.");
            return;
        }

        var ctx = document.getElementById("OffenseCanvas");

        if (!ctx) {
            console.error("Canvas element 'OffenseCanvas' not found.");
            return;
        }

        ctx = ctx.getContext("2d");

        var robotNames = [];
        var avgTotalPoints = [];
        var offenseActivities = [];
        var offensivePerformanceRate = [];
        var colors = [];

        function getRandomColor() {
            return "rgb(" + (Math.floor(Math.random() * 255)) + "," +
                            (Math.floor(Math.random() * 255)) + "," +
                            (Math.floor(Math.random() * 255)) + ")";
        }

        data.forEach(entry => {
            robotNames.push(entry.robot);
            avgTotalPoints.push(entry.avg_total_points);
            offenseActivities.push(entry.offense_activities);
            offensivePerformanceRate.push(entry.offensive_performance_rate * 100); // Convert rate to percentage
            colors.push(getRandomColor());
        });

        new Chart(ctx, {
            type: 'bar', // Base type is bar
            data: {
                labels: robotNames,
                datasets: [
                    {
                        label: "Avg Total Points",
                        type: "bar",
                        backgroundColor: "rgba(255, 99, 132, 0.7)",
                        borderColor: "rgba(255, 99, 132, 1)",
                        borderWidth: 1,
                        data: avgTotalPoints,
                        stack: "Stack 1"
                    },
                    {
                        label: "Offensive Activities",
                        type: "bar",
                        backgroundColor: "rgba(54, 162, 235, 0.7)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 1,
                        data: offenseActivities,
                        stack: "Stack 1"
                    },
                    {
                        label: "Offensive Performance Rate (%)",
                        type: "line", // Use a line chart for performance rate
                        borderColor: "rgba(75, 192, 192, 1)",
                        backgroundColor: "rgba(75, 192, 192, 0.3)",
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y-axis-2', // Separate y-axis
                        data: offensivePerformanceRate
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    x: {
                        stacked: true // Enable stacked bars
                    },
                    y: {
                        stacked: true, // Stack bars
                        title: { display: true, text: "Points & Activities" },
                        beginAtZero: true
                    },
                    "y-axis-2": {
                        position: "right", // Separate y-axis for percentage
                        title: { display: true, text: "Success Rate (%)" },
                        grid: { drawOnChartArea: false } // Prevents overlap with bars
                    }
                }
            }
        });
    });
}


       



    </script>

</body>
</html>
