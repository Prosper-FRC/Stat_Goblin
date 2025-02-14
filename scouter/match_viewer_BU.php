<?php
require_once '../php/database_connection.php'; // Ensure correct path

try {
    // Create a PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Step 1: Get Unique Event Names
    $event_query = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name ASC");
    $events = $event_query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRC Robot Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f4;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .dropdown-container {
            text-align: center;
            margin-bottom: 20px;
        }
        select {
            padding: 8px;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #333;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #e1e1e1;
        }
        .container {
            width: 90%;
            margin: auto;
            background: white;
            padding: 20px;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
        }
    </style>
    <script>
        function fetchData() {
            let eventName = document.getElementById("eventDropdown").value;
            let xhr = new XMLHttpRequest();
            xhr.open("GET", "fetch_robot_data.php?event_name=" + encodeURIComponent(eventName), true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("robot-data").innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }
    </script>
</head>
<body>

    <div class="container">
        <h2>FRC Robot Data</h2>

        <!-- Event Selection Dropdown -->
        <div class="dropdown-container">
            <label for="eventDropdown"><strong>Select an Event:</strong></label>
            <select id="eventDropdown" onchange="fetchData()">
                <option value="">-- Select Event --</option>
                <?php foreach ($events as $event) { ?>
                    <option value="<?php echo htmlspecialchars($event['event_name']); ?>">
                        <?php echo htmlspecialchars($event['event_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- Robot Data Table -->
        <div id="robot-data">
            <p style="text-align: center;">Select an event to see robot data.</p>
        </div>
    </div>

</body>
</html>
