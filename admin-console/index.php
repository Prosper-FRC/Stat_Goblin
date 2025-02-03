<?php
session_start();

// Check if the user is logged in, if not redirect to the login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /../scouting/php/login.php');
    exit;
}

// Database connection details
$host = 'localhost';
$dbname = 'frc_scouting';
$username = 'root';
$password = 'pw123456';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//    // Toggle event active status
//    if (isset($_POST['toggle_event'])) {
//        $event_id = $_POST['event_id'];
//        $status = $_POST['status'];
//        $sql = "UPDATE frc_events SET active = :status WHERE id = :event_id";
//        $stmt = $pdo->prepare($sql);
//        $stmt->bindParam(':event_id', $event_id);
//        $stmt->bindParam(':status', $status);
//        $stmt->execute();
//    }
//
    // Generate a new 4-digit code
    if (isset($_POST['generate_code'])) {
        $new_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $sql = "UPDATE codes SET is_active = 0";
        $pdo->prepare($sql)->execute();
        $sql = "INSERT INTO codes (code, is_active) VALUES (:new_code, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':new_code', $new_code);
        $stmt->execute();
    }

    // Begin Match: Insert or update match
    if (isset($_POST['begin_match'])) {
        $year = $_POST['year'];
        $event = $_POST['event'];
        $match_number = $_POST['match_number'];

        if (!empty($year) && !empty($event) && !empty($match_number)) {
            $sql = "UPDATE matches SET active = 0";
            $pdo->prepare($sql)->execute();

            $sql = "SELECT COUNT(*) FROM matches WHERE year = :year AND event = :event AND match_number = :match_number";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':year' => $year,
                ':event' => $event,
                ':match_number' => $match_number,
            ]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $sql = "UPDATE matches SET start_time = NOW(), pause = 0, paused_at = NULL, total_pause_duration = 0, active = 1
                        WHERE year = :year AND event = :event AND match_number = :match_number";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':year' => $year,
                    ':event' => $event,
                    ':match_number' => $match_number,
                ]);
            } else {
                $sql = "INSERT INTO matches (year, event, match_number, start_time, pause, total_pause_duration, active)
                        VALUES (:year, :event, :match_number, NOW(), 0, 0, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':year' => $year,
                    ':event' => $event,
                    ':match_number' => $match_number,
                ]);
            }
        }
    }

// Pause/Unpause Match
if (isset($_POST['toggle_pause'])) {
    $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
    $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    if ($activeMatch) {
        if ($activeMatch['pause'] == 0) {
            // Pause the match: Store the current timestamp in `paused_at`
            $currentTime = date('Y-m-d H:i:s');
            $sql = "UPDATE matches SET pause = 1, paused_at = :current_time WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':current_time' => $currentTime, ':id' => $activeMatch['id']]);
        } else {
            // Unpause the match: Calculate the duration paused (in seconds)
            $pausedAtTimestamp = strtotime($activeMatch['paused_at']);
            $pausedDuration = time() - $pausedAtTimestamp;

            // Add the paused duration to `total_pause_duration` and reset `paused_at`
            $sql = "UPDATE matches 
                    SET pause = 0, 
                        total_pause_duration = total_pause_duration + :paused_duration, 
                        paused_at = NULL 
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':paused_duration' => $pausedDuration, ':id' => $activeMatch['id']]);
        }
    }
}


    // Fetch active events and the current year's events
 //   $sql = "SELECT * FROM frc_events WHERE active = 1 OR year = YEAR(CURDATE())";
 //   $events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch only active events 
    $sql = "SELECT * FROM active_event limit 1";
    $acitveEvents = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


    // Fetch the current active code
    $sql = "SELECT * FROM codes WHERE is_active = 1 LIMIT 1";
    $active_code = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    // Fetch the current active match
    $sql = "SELECT * FROM matches WHERE active = 1 LIMIT 1";
    $activeMatch = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console</title>
    <style>
        body {
            font-family: "Roboto", sans-serif;
            color: #111;
        }
        h1, h2, h3 {
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .scrollable-table {
            max-height: 300px;
            overflow-y: auto;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>

<h1>Admin Console</h1>

<!-- Feature 1: Manage Events -->
<h2>Manage Events</h2>
<!--<div class="scrollable-table">
    <table>
        <thead>
            <tr>
                <th>Event Name</th>
                <th>Year</th>
                <th>Active Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?= htmlspecialchars($event['event_name']) ?></td>
                    <td><?= htmlspecialchars($event['year']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            <input type="hidden" name="status" value="<?= $event['active'] == 1 ? 0 : 1 ?>">
                            <button type="submit" name="toggle_event">
                                <?= $event['active'] == 1 ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>  -->

<!-- Feature 2: Generate New Code -->
<h2>Generate New Code</h2>
<div>
    <form method="POST">
        <button type="submit" name="generate_code">Generate New Code</button>
    </form>
    <?php if ($active_code): ?>
        <p>Current Active Code: <?= htmlspecialchars($active_code['code']) ?></p>
    <?php else: ?>
        <p>No active code found.</p>
    <?php endif; ?>
</div>

<!-- Feature 3: Match Timer -->

<!-- Feature: Start a New Match -->
<h2>Start a New Match</h2>
<form method="POST">
    <label for="year">Year:</label>
    <select name="year" id="year" required>
        <option value="">Select Year</option>
      
            <option value="2025">2025</option>
             <option value="2026">2026</option>
              <option value="2027">2027</option>
               <option value="2028">2028</option>
                <option value="2029">2029</option>
     
    </select>

    <label for="event">Event:</label>
    <select name="event" id="event" required>
        <option value="">Select Event</option>
        <?php foreach ($acitveEvents as $event): ?>
            <option value="<?= htmlspecialchars($event['event_name']) ?>"><?= htmlspecialchars($event['event_name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="match_number">Match Number:</label>
    <input type="number" name="match_number" id="match_number" required min="1" placeholder="Enter Match Number">

    <button type="submit" name="begin_match">Begin Match</button>
</form>



<h2>Match Timer</h2>
<div>
    <?php if ($activeMatch): ?>
        <p>Match <?= htmlspecialchars($activeMatch['match_number']) ?> for <?= htmlspecialchars($activeMatch['event']) ?> (<?= htmlspecialchars($activeMatch['year']) ?>) is active.</p>
        <p id="timer">Loading...</p>
        <form method="POST">
            <button type="submit" name="toggle_pause">
                <?= $activeMatch['pause'] == 0 ? 'Pause' : 'Unpause' ?>
            </button>
        </form>
    <?php else: ?>
        <p>No active match.</p>
    <?php endif; ?>
</div>

<script>
let startTime = <?= json_encode($activeMatch['start_time'] ?? null) ?>;
let totalPause = <?= json_encode($activeMatch['total_pause_duration'] ?? 0) ?>;
let isPaused = <?= json_encode($activeMatch['pause'] ?? 0) ?>;
const matchId = <?= json_encode($activeMatch['id'] ?? null) ?>;

function updateTimer() {
    const timerElement = document.getElementById('timer');
    if (!startTime) {
        timerElement.textContent = "No active match.";
        return;
    }

    const startTimeMs = new Date(startTime).getTime();
    let elapsedSeconds = (Date.now() - startTimeMs) / 1000 - totalPause;

    if (isPaused) {
        timerElement.textContent = "Paused";
        return;
    }

    const remainingSeconds = Math.max(150 - elapsedSeconds, 0);

    if (remainingSeconds === 0) {
        timerElement.textContent = "Match Over";
        clearInterval(timerInterval);

        // Deactivate the match when the timer reaches 0
        if (matchId) {
            fetch('/../php/deactivate_match.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ match_id: matchId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("Match deactivated successfully.");
                } else {
                    console.error("Failed to deactivate match.");
                }
            })
            .catch(error => console.error("Error:", error));
        }
        return;
    }

    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = Math.floor(remainingSeconds % 60);
    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
}

const timerInterval = setInterval(updateTimer, 1000);
updateTimer();


</script>

</body>
</html>
