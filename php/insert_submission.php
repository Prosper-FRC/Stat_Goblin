<?php
// Database connection details

$host = 'localhost';
$dbname = 'frc_scouting';
$username = 'root';
$password = 'pw123456';

try {
    // Create a PDO instance to connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get the JSON data from the client (use `file_get_contents` to capture JSON input)
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if all necessary data is present
    if (!isset($data['event_name'], $data['match_no'], $data['time_sec'], 
              $data['robot'], $data['alliance'], $data['action'], $data['location'], $data['result'], $data['points'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
        exit;
    }

    // Get the client's IP address (This should be set on the server-side)
    $ip_address = $_SERVER['REMOTE_ADDR']; // Get the client's IP address from server variables

    // Extract values from the data
    $event_name = $data['event_name'];
    $match_no = $data['match_no'];
    $time_sec = $data['time_sec'];
    $robot = $data['robot'];
    $alliance = $data['alliance'];
    $action = $data['action'];
    $location = $data['location'];
    $result = $data['result'];
    $points = $data['points'];

    


if ($action === 'delete_action') { 
    // Prepare SQL query to delete the latest matching entry
    $sql = "DELETE FROM scouting_submissions 
            WHERE id = (SELECT id FROM (SELECT MAX(id) AS id FROM scouting_submissions 
                                        WHERE ip_address = :ip_address 
                                        AND event_name = :event_name 
                                        AND match_no = :match_no) AS subquery)"; 
} else { 
    // Prepare SQL query to insert data
    $sql = "INSERT INTO scouting_submissions (ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points)
            VALUES (:ip_address, :event_name, :match_no, :time_sec, :robot, :alliance, :action, :location, :result, :points)";
}
    // Prepare the statement
    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->bindParam(':event_name', $event_name);
    $stmt->bindParam(':match_no', $match_no);
    $stmt->bindParam(':time_sec', $time_sec);
    $stmt->bindParam(':robot', $robot);
    $stmt->bindParam(':alliance', $alliance);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':result', $result);
    $stmt->bindParam(':points', $points);

    // Execute the query
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Data inserted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to insert data']);
    }

} catch (PDOException $e) {
    // Catch any database connection errors
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
?>
